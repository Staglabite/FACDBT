import Swal from 'sweetalert2';
import axios from 'axios';

class swallInformation {
    constructor() {
        this.apiUrl = 'HITUAM/api/bd/configuration/variable';
    }

    /**
     * Ambil data konfigurasi dari database
     * @param {string} variable - Nama variable konfigurasi
     * @returns {Promise}
     */
    async getConfiguration(variable) {
        try {
            const response = await axios.get(this.apiUrl, {
                params: { variable }
            });
            return response.data;
        } catch (error) {
            console.error('Error fetching configuration:', error);
            throw error;
        }
    }

    /**
     * Tampilkan popup Kebijakan Privasi
     */
    async showPrivacyPolicy() {
        try {
            const data = await this.getConfiguration('pengaturan_privasi_anda');

            Swal.fire({
                html: data.content || 'Konten tidak tersedia',
                confirmButtonText: 'Tutup',
                width: '800px',
                customClass: {
                    popup: 'swal-wide',
                    htmlContainer: 'text-start'
                }
            });
        } catch (error) {
            Swal.fire({
                title: 'Error',
                text: 'Gagal memuat Kebijakan Privasi',
                icon: 'error',
                confirmButtonText: 'Tutup'
            });
        }
    }

    /**
     * Tampilkan popup Legal Cookie
     */
    async showLegalCookie() {
        try {
            const data = await this.getConfiguration('legal_cookies');

            Swal.fire({
                html: data.content || 'Konten tidak tersedia',
                confirmButtonText: 'Tutup',
                width: '800px',
                customClass: {
                    popup: 'swal-wide',
                    htmlContainer: 'text-start'
                }
            });
        } catch (error) {
            Swal.fire({
                title: 'Error',
                text: 'Gagal memuat Legal Cookie',
                icon: 'error',
                confirmButtonText: 'Tutup'
            });
        }
    }

    /**
     * Initialize event listeners untuk footer links
     */
    initializeFooterLinks() {
        $(document).on('click', '#btn-privacy-policy', (e) => {
            e.preventDefault();
            this.showPrivacyPolicy();
        });

        $(document).on('click', '#btn-legal-cookie', (e) => {
            e.preventDefault();
            this.showLegalCookie();
        });
    }
}

// ✅ EKSPOR KE WINDOW OBJECT UNTUK AKSES GLOBAL
window.swallInformation = swallInformation;

// ✅ BUAT INSTANCE GLOBAL
window.swallInfo = new swallInformation();

// Auto-initialize saat DOM ready
document.addEventListener('DOMContentLoaded', () => {
    window.swallInfo.initializeFooterLinks();
});

// Export untuk module system
export default swallInformation;
