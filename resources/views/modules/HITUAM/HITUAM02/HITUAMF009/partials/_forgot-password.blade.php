<div class="app-brand justify-content-center mb-6">
    <a href="{{ url('/') }}" class="app-brand-link">
        <img src="{{ asset('assets/img/point-logo.png') }}" alt="Logo ASTEMO" srcset="" width="200">
    </a>
</div>

<h4 class="mb-1 text-center">Forgot Password? 🔒</h4>
<p class="mb-6 text-center">Enter your email and we'll send you instructions to reset your password</p>
<form id="formForgotPassword" class="mb-4 w-100" action="{{ route('attemt.forgot') }}" method="POST">
    @csrf
    <div class="mb-6">
        <label for="email" class="form-label">Email</label>
        <input type="text" class="form-control @error('email') is-invalid @enderror" id="email" name="email"
            placeholder="Enter your email" value="{{ old('email') }}" autofocus>
        @error('email')
        <div class="invalid-feedback">
            {{ $message }}
        </div>
        @enderror
    </div>
    <div class="mb-6">
        <button class="btn btn-primary d-grid w-100" type="submit">Send Reset Link</button>
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
