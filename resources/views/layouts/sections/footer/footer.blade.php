@php
$containerFooter = isset($configData['contentLayout']) && $configData['contentLayout'] === 'compact' ? 'container-xxl' : 'container-fluid';
$whatsapp = $configData['contact_phone_number'] ?? '#';
$email = $configData['contact_email'] ?? '#';
@endphp

<!-- Footer-->
<footer class="content-footer footer bg-footer-theme">
    <div class="{{ $containerFooter }}">
        <div class="footer-container d-flex align-items-center justify-content-between py-4 flex-md-row flex-column">
            <div class="text-body">
                &#169; Copyright
                <script>
                    document.write(new Date().getFullYear());
                </script>
                , PT Astemo Bekasi Manufacturing. All Right Reserved.
            </div>
            <div class="d-none d-lg-inline-block">
                <a href="https://wa.me/{{ $whatsapp }}" class="footer-link me-4">Whatsapp</a>
                <a href="mailto:{{ $email }}?subject=Pertanyaan&body=Halo%20saya%20ingin%20bertanya" class="footer-link me-4">Email</a>
                <a href="#" class="footer-link me-4" id="btn-privacy-policy">Kebijakan Privasi</a>
                <a href="#" class="footer-link me-4" id="btn-legal-cookie">Legal Cookie</a>
            </div>
        </div>
    </div>
</footer>
<!-- / Footer -->
