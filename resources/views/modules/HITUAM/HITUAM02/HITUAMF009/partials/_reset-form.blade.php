<div class="app-brand justify-content-center mb-6">
    <a href="{{ url('/') }}" class="app-brand-link">
        <img src="{{ asset('assets/img/point-logo.png') }}" alt="Logo ASTEMO" srcset="" width="200">
    </a>
</div>
<h4 class="mb-1 text-center">Reset Password 🔒</h4>
<p class="mb-6 text-center">Your new password must be different from previously used passwords</p>
<form class="mb-4 w-100" action="{{ route('reset.post') }}" method="POST">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}" class="form-control">
    <div class="mb-6 form-password-toggle">
        <label class="form-label" for="password">Password</label>
        <div class="input-group input-group-merge">
            <input type="password" id="password" class="form-control" name="password"
                placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                aria-describedby="password" />
            <span class="input-group-text cursor-pointer"><i class="ti tabler-eye-off"></i></span>
        </div>
    </div>
    <div class="mb-6 form-password_confirmation-toggle">
        <label class="form-label" for="password_confirmation">Confirmation Password</label>
        <div class="input-group input-group-merge">
            <input type="password" id="password_confirmation" class="form-control" name="password_confirmation"
                placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                aria-describedby="password_confirmation" />
            <span class="input-group-text cursor-pointer"><i class="ti tabler-eye-off"></i></span>
        </div>
    </div>
    <div class="mb-6">
        <button class="btn btn-primary d-grid w-100" type="submit">Set new password</button>
    </div>
    <div class="text-center">
        <a href="{{ route('login') }}" class="d-flex justify-content-center">
            <i class="icon-base ti tabler-chevron-left scaleX-n1-rtl me-1_5"></i>
            Back to login
        </a>
    </div>
</form>

<div class="mb-4 text-no-wrap d-flex justify-content-center">
    <a href="#" id="btn-privacy-policy" class="privacy-link">Kebijakan Privasi</a>
    &nbsp;|&nbsp;
    <a href="#" id="btn-legal-cookie" class="privacy-link">Legal dan Cookies</a>
</div>
