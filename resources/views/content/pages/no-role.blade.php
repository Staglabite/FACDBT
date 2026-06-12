@php
    $customizerHidden = 'customizer-hide';
    $configData = Helper::appClasses();
@endphp

@extends('layouts/blankLayout')

@section('title', 'Error - Pages')

@section('page-style')
    <!-- Page -->
    @vite(['resources/assets/vendor/scss/pages/page-misc.scss'])
@endsection


@section('content')
    <!-- Error -->
    <div class="container-xxl container-p-y">
        <div class="misc-wrapper">
            <h4 class="mb-2 mx-2" style="line-height: 6rem; font-size: 3rem">Login Successful</h4>
            <p class="mb-6 mx-2" style="font-size: 1rem">There is no menu set for you. Please confirm IT.</p>
            <button class="btn btn-danger mb-10"
                onclick="event.preventDefault(); document.getElementById('logout-form').submit();">Logout</button>
            <form method="POST" id="logout-form" action="{{ route('logout') }}">
                @csrf
            </form>
            <div class="mt-4">
                <img src="{{ asset('assets/img/illustrations/page-misc-under-maintenance.png') }}"
                    alt="page-misc-under-maintenance" width="550" class="img-fluid" />
            </div>
        </div>
    </div>
    <div class="container-fluid misc-bg-wrapper">
        <img src="{{ asset('assets/img/illustrations/bg-shape-image-' . $configData['theme'] . '.png') }}" height="355"
            alt="page-misc-error" data-app-light-img="illustrations/bg-shape-image-light.png"
            data-app-dark-img="illustrations/bg-shape-image-dark.png" />
    </div>
    <!-- /Error -->
@endsection
