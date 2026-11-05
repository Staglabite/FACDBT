import axios from 'axios';
import { _formToJson, _showInvalidError, toast } from '../../helpers';

class Login {
    #formElement;

    init() {
        this.#formElement = document.getElementById('formAuthentication');
        if (!this.#formElement) {
            return;
        }
        this.#events();
    }

    #events() {
        this.#formElement.addEventListener('submit', event => {
            event.preventDefault();
            this.#login();
        });
    }

    #login() {
        let data = _formToJson(this.#formElement);
        axios
            .post('/HITUAM/auth/login', data)
            .then(response => {
                if (response.data && response.data.success) {
                    console.log(response.data);

                    // this.#showPrivacyModal(response.data.redirect || '/', response.data.name, response.data.privacy);
                    this.#checkPrivacyAgreement(response.data.redirect || '/', response.data.name, response.data.privacy);
                }
            })
            .catch(error => {
                if (error.response && error.response.status === 422) {
                    const errors = error.response.data.errors;
                    _showInvalidError(errors);
                } else {
                    toast.error(error.response.data.message);
                }
            });
    }

    #checkPrivacyAgreement(redirectUrl, name, privacy) {
        // Cek status persetujuan privacy dari server
        axios
            .get('/HITUAM/auth/check-privacy-agreement')
            .then(response => {
                if (response.data.agreed) {
                    // Jika sudah setuju dalam 1 hari terakhir, langsung redirect
                    window.location.href = redirectUrl;
                } else {
                    // Jika belum, tampilkan modal
                    this.#showPrivacyModal(redirectUrl, name, privacy);
                }
            })
            .catch(error => {
                console.error('Error checking privacy agreement:', error);
                // Jika error, tetap tampilkan modal untuk keamanan
                this.#showPrivacyModal(redirectUrl, name, privacy);
            });
    }

    #showPrivacyModal(redirectUrl, name, privacy) {
        Swal.fire({
            html: `
                <div style="text-align: left; padding: 20px;">
                    <strong>PERSETUJUAN PRIVACY IDBM</strong>
                    <hr>
                    <h4 style="margin-bottom: 15px;">Selamat Datang ` + name + `</h4>
                    <p style="margin-bottom: 10px; font-weight: 600;">Pengaturan Privasi Anda</p>
                    <p style="line-height: 1.6; color: #666;">
                        ` + privacy + `
                    </p>
                </div>
            `,
            width: '600px',
            showCloseButton: true,
            showCancelButton: true,
            confirmButtonText: 'Ya, Saya Setuju',
            cancelButtonText: 'Tidak Setuju',
            confirmButtonColor: '#6f42c1',
            cancelButtonColor: '#dc3545',
            buttonsStyling: false,
            reverseButtons: true,
            allowOutsideClick: false,
            didOpen: () => {
                // Custom style untuk popup
                const popup = Swal.getPopup();
                popup.style.borderRadius = '8px';

                // Custom style untuk html container
                const htmlContainer = Swal.getHtmlContainer();
                htmlContainer.style.padding = '0';
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // User menyetujui, simpan ke cache
                this.#savePrivacyAgreement(true, redirectUrl);
            } else if (result.dismiss === Swal.DismissReason.cancel || result.dismiss === Swal.DismissReason.close) {
                // User tidak setuju atau close modal, logout dan stay di login page
                this.#handlePrivacyRejection();
            }
        });
    }

    #savePrivacyAgreement(agreed, redirectUrl) {
        axios
            .post('/HITUAM/auth/save-privacy-agreement', {
                agreed: agreed
            })
            .then(response => {
                if (response.data.success) {
                    // Redirect setelah berhasil menyimpan
                    window.location.href = redirectUrl;
                }
            })
            .catch(error => {
                console.error('Error saving privacy agreement:', error);
                toast.error('Gagal menyimpan persetujuan privacy');
                // Tetap redirect meskipun gagal menyimpan
                window.location.href = redirectUrl;
            });
    }

    #handlePrivacyRejection() {
        // Logout user karena tidak menyetujui privacy
        axios
            .post('/HITUAM/auth/logout')
            .then(response => {
                toast.success('Anda harus menyetujui kebijakan privasi untuk melanjutkan');
                // Stay di halaman login (reload untuk clear form)
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            })
            .catch(error => {
                console.error('Error during logout:', error);
                toast.error('Terjadi kesalahan saat logout');
                // Tetap reload untuk keamanan
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            });
    }
}

document.addEventListener('DOMContentLoaded', function () {
    new Login().init();
});
