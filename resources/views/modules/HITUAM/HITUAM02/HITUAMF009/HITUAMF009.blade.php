@php
    $customizerHidden = 'customizer-hide';
    $currentUrl = url()->current();
    $isLoginPage = str_contains($currentUrl, '/auth/login');
    $isForgotPasswordPage = str_contains($currentUrl, '/auth/forgot-password');
    $isResetPasswordPage = str_contains($currentUrl, '/auth/reset-password');
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Login')

@section('vendor-style')
    @vite(['resources/assets/vendor/libs/@form-validation/form-validation.scss'])
@endsection

@section('page-style')
    @vite(['resources/assets/vendor/scss/pages/page-auth.scss'])
    <style>
        .login-illustration-panel {
            background-color: #f5f5f5;
        }

        .login-illustration-panel.is-login-page {
            background-image: url('{{ asset('assets/img/Atemo Logo_transp.png') }}');
            background-position: center;
            background-repeat: no-repeat;
            background-size: 60%;
        }
    </style>
@endsection

@section('vendor-script')
    @vite(['resources/assets/vendor/libs/@form-validation/popular.js', 'resources/assets/vendor/libs/@form-validation/bootstrap5.js', 'resources/assets/vendor/libs/@form-validation/auto-focus.js'])
@endsection

@section('content')
    <div class="container-fluid p-0">
        <div class="row g-0">
            <div class="col-md-6 col-lg-8 d-none d-md-block">
                <div
                    class="d-flex flex-column justify-content-between h-100 p-10 login-illustration-panel {{ $isLoginPage ? 'is-login-page' : '' }}">
                    <div class="d-flex align-items-center justify-content-center mt-10">
                        @if ($isLoginPage)
                        @elseif($isForgotPasswordPage)
                            <img src="{{ asset('assets/img/pages/auth-forgot-password-illustration-light.png') }}"
                                alt="Forgot Password Illustration" class="img-fluid" style="max-height: 60vh;">
                        @elseif($isResetPasswordPage)
                            <img src="{{ asset('assets/img/pages/auth-reset-password-illustration-dark.png') }}"
                                alt="Forgot Password Illustration" class="img-fluid" style="max-height: 60vh;">
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4 d-flex justify-items-center justify-content-center card"
                style="min-height: 100vh;">
                <div class="container-md">
                    @if ($isLoginPage)
                        @include('modules.HITUAM.HITUAM02.HITUAMF009.partials._login-form')
                    @elseif($isForgotPasswordPage)
                        @include('modules.HITUAM.HITUAM02.HITUAMF009.partials._forgot-password')
                    @elseif($isResetPasswordPage)
                        @include('modules.HITUAM.HITUAM02.HITUAMF009.partials._reset-form')
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@section('page-script')
    @vite(['resources/assets/js/pages-auth.js', 'resources/js/pages/hituam/login.js', 'resources/js/pages/hituam/swal.js'])
    @if (session('swal'))
        <script>
            // Buat data tersedia secara global
            window.swalData = @json(session('swal'));
        </script>
    @endif
@endsection
