@extends('layouts/layoutMaster')

@section('title', 'HITUAM - Master Data User Roles')

@section('page-style')
    @vite(['resources/assets/vendor/libs/select2/select2.scss'])
@endsection

@section('content')
    <div class="card">
        <div class="user-profile-header d-flex flex-column flex-lg-row text-sm-start text-center mb-5">
            <div class="flex-grow-1 mt-3 mt-lg-5">
                <div
                    class="d-flex align-items-md-end align-items-sm-start align-items-center justify-content-md-between justify-content-start mx-5 flex-md-row flex-column gap-4">
                    <div class="user-profile-info">
                        <div class="mb-2 mt-lg-6">
                            <h4 class="mb-0 d-inline">{{ $user->VUSERNAME }}</h4><span
                                class="ms-2">{{ $user->supplier_user?->VSUPPLIER_NAME }}</span>
                        </div>
                        <ul
                            class="list-inline mb-0 d-flex align-items-center flex-wrap justify-content-sm-start justify-content-center gap-4 my-2">
                            <li class="list-inline-item d-flex gap-2 align-items-center">
                                <i class='icon-base ti tabler-world'></i>
                                <span class="fw-medium">
                                    {{ $user->roles->pluck('VROLENAME')->implode(', ') }}
                                </span>
                            </li>
                            <li class="list-inline-item d-flex gap-2 align-items-center">
                                <i class='icon-base ti tabler-calendar'></i>
                                <span class="fw-medium"> Created
                                    {{ \Carbon\Carbon::parse($user->DCREA)->format('M Y') }}
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-6">
        <div class="card-header">
            <h5>Profile</h5>
        </div>
        <div class="card-body">
            <form id="userProfileForm">
                <input type="hidden" value="{{ $user->supplier_user == null ? 'internal' : 'external' }}"
                    name="user_type" />
                <div class="row mb-6">
                    <div class="col-lg-6">
                        <div class="row mb-6">
                            <label class="col-sm-3 col-form-label" for="username">Username</label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" id="username" name="username"
                                    placeholder="John Doe" value="{{ $user->VUSERNAME }}" disabled />
                            </div>
                        </div>

                        <div class="row mb-6">
                            <label class="col-sm-3 col-form-label" for="email">Email</label>
                            <div class="col-sm-9">
                                <input type="email" class="form-control" id="email" name="email"
                                    placeholder="john@example.com" value="{{ $user->VEMAIL }}" disabled />
                            </div>
                        </div>
                        <div class="row mb-6">
                            <label class="col-sm-3 col-form-label" for="role">Role</label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" id="role"
                                    value="{{ $user->roles->pluck('VROLENAME')->implode(', ') }}" readonly disabled />
                            </div>
                        </div>
                    </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card mt-6">
        <div class="card-header">
            <h5>Change Password</h5>
        </div>
        <div class="card-body">
            <form id="changePasswordForm">
                <div class="row mb-6 form-password-toggle">
                    <label class="col-sm-2 col-lg-2 col-form-label" for="current_password">Current Password</label>
                    <div class="col-sm-10 col-lg-4">
                        <div class="input-group input-group-merge">
                            <input type="password" class="form-control" id="current_password" name="current_password"
                                placeholder="********" aria-describedby="current_password" required />
                            <span class="input-group-text cursor-pointer">
                                <i class="icon-base ti tabler-eye-off"></i>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="row mb-6 form-password-toggle">
                    <label class="col-sm-2 col-lg-2 col-form-label" for="new_password">New Password</label>
                    <div class="col-sm-10 col-lg-4">
                        <div class="input-group input-group-merge">
                            <input type="password" class="form-control" id="new_password" name="new_password"
                                placeholder="********" aria-describedby="new_password" required />
                            <span class="input-group-text cursor-pointer">
                                <i class="icon-base ti tabler-eye-off"></i>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="row mb-6 form-password-toggle">
                    <label class="col-sm-2 col-lg-2 col-form-label" for="confirm_password">Confirm Password</label>
                    <div class="col-sm-10 col-lg-4">
                        <div class="input-group input-group-merge">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                placeholder="********" aria-describedby="confirm_password" required />
                            <span class="input-group-text cursor-pointer">
                                <i class="icon-base ti tabler-eye-off"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </form>

            <div class="row">
                <div class="col-12 col-md-6 text-end">
                    <button type="submit" form="changePasswordForm" class="btn btn-primary"
                        id="update-user-password">Update</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('page-script')
    @vite('resources/js/pages/hituam/hituam01/user/user.js')
@endsection
