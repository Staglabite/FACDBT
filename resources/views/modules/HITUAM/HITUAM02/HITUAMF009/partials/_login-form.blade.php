@section('page-style')
<style>

</style>
@endsection
<!-- Logo -->
<div class="app-brand justify-content-center mb-6">
    <a href="{{ url('/') }}" class="app-brand-link">
        <img src="{{ asset('assets/img/point-logo.png') }}" alt="Logo ASTEMO" srcset="" width="200">
    </a>
</div>
<!-- /Logo -->
<h4 class="mb-1 text-center">Welcome to Astemo! 👋</h4>
<p class="mb-6 text-center">Please sign-in to your account and start the adventure</p>

<form id="formAuthentication" class="mb-4 w-100" data-default-submit="false">
    @csrf
    <div class="mb-6 form-control-validation">
        <label for="username" class="form-label">Username</label>
        <input type="text" class="form-control" id="username" name="username" placeholder="Enter your username"
            autofocus>
    </div>
    <div class="mb-6 form-password-toggle form-control-validation">
        <label class="form-label" for="password">Password</label>
        <div class="input-group input-group-merge">
            <input type="password" id="password" class="form-control" name="password"
                placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                aria-describedby="password" />
            <span class="input-group-text cursor-pointer"><i class="ti tabler-eye-off"></i></span>
        </div>
    </div>
    <div class="my-8">
        <div class="d-flex justify-content-between">
            <div class="form-check mb-0 ms-2"></div>
            <a href="{{ route('forgot.password') }}">
                <p class="mb-0">Forgot Password?</p>
            </a>
        </div>
    </div>
</form>
<div class="mb-6">
    <button class="btn btn-primary w-100" type="submit" form="formAuthentication">Login</button>
</div>

<div class="mb-4 text-no-wrap d-flex justify-content-center">
    <a href="#" id="btn-privacy-policy" class="privacy-link">Kebijakan Privasi</a>
    &nbsp;|&nbsp;
    <a href="#" id="btn-legal-cookie" class="privacy-link">Legal dan Cookies</a>
</div>
